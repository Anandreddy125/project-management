pipeline {
    agent any
    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }
    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }
    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }
    
    // Modified trigger - only for tags
    triggers {
        pollSCM('H/5 * * * *')  // This will detect new tags
    }
    
    stages {
        stage('Clean Workspace') {
            steps { cleanWs() }
        }
        
        stage('Checkout Code') {
            steps {
                script {
                    // Check if this build was triggered by a tag
                    def isTagBuild = false
                    def tagName = ""
                    
                    try {
                        // Try to get the latest tag
                        tagName = sh(
                            script: "git ls-remote --tags origin | grep -v '{}' | sort -V | tail -1 | sed 's/.*\\///g'",
                            returnStdout: true
                        ).trim()
                        
                        if (tagName) {
                            isTagBuild = true
                            echo "🏷️ Tag detected: ${tagName}"
                        }
                    } catch (Exception e) {
                        echo "No tags found or error detecting tags"
                    }
                    
                    if (isTagBuild && tagName) {
                        // Checkout the specific tag
                        checkout([$class: 'GitSCM',
                            branches: [[name: "refs/tags/${tagName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = "master"  // Tags are typically on master
                        env.BUILD_TAG = tagName
                        env.IS_TAG_BUILD = "true"
                    } else {
                        // Manual build or parameter-based build
                        def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                        echo "🔄 Manual build - Checking out branch: ${branchName}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/${branchName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                        env.ACTUAL_BRANCH = branchName
                        env.IS_TAG_BUILD = "false"
                    }
                }
            }
        }
        
        stage('Determine Environment') {
            steps {
                script {
                    if (env.IS_TAG_BUILD == "true") {
                        // Tag builds always go to production
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                        echo "🚀 Tag-triggered build - deploying to PRODUCTION"
                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    
                    echo """
                    🔍 Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    Tag Build: ${env.IS_TAG_BUILD}
                    Build Tag: ${env.BUILD_TAG ?: 'N/A'}
                    """
                }
            }
        }
        
        stage('Generate Docker Tag') {
            steps {
                script {
                    def imageTag = ""
                    
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (env.IS_TAG_BUILD == "true" && env.BUILD_TAG) {
                        // Use the Git tag as Docker tag
                        imageTag = env.BUILD_TAG
                        echo "🏷️ Using Git tag as Docker tag: ${imageTag}"
                    } else if (env.TAG_TYPE == "commit") {
                        def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                        imageTag = "staging-${commitId}"
                    } else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()
                        if (!tagName) {
                            error("Tag not found. Stopping build.")
                        }
                        imageTag = tagName
                    }
                    
                    env.IMAGE_TAG = imageTag
                    echo "🚀 FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }
        
        // ... rest of your stages remain the same
    }
    
    post {
        success {
            script {
                def buildType = env.IS_TAG_BUILD == "true" ? "Tag-Triggered" : "Manual"
                slackSend(
                    channel: 'C09M08HUK8W',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: ":white_check_mark: *${buildType} Deployment Successful!*\n\n*Env:* ${env.DEPLOY_ENV}\n*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n*Tag:* ${env.BUILD_TAG ?: 'N/A'}\n<${env.BUILD_URL}|View Build>"
                )
            }
        }
        failure {
            script {
                slackSend(
                    channel: '#C09M08HUK8W',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Build Failed!*\n\n*Env:* ${env.DEPLOY_ENV}\n<${env.BUILD_URL}|View Logs>"
                )
            }
        }
        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}