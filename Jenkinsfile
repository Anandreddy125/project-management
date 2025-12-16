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
    triggers {
        // SCM polling - will detect both branch and tag changes
        pollSCM('H/2 * * * *')  // Poll every 2 minutes
    }
    stages {
        stage('Clean Workspace') {
            steps { cleanWs() }
        }
        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo ":small_blue_diamond: Checking out branch: ${branchName}"
                    
                    // IMPORTANT: Check if this is triggered by a tag push
                    def isTagPush = false
                    def tagName = ""
                    
                    // Check GIT_BRANCH format - tags come as origin/tags/v1.0.0
                    if (env.GIT_BRANCH && env.GIT_BRANCH.contains('tags/')) {
                        isTagPush = true
                        tagName = env.GIT_BRANCH.replace('origin/tags/', '')
                        echo "🎯 TAG PUSH detected: ${tagName}"
                        // Force build as production from tag
                        branchName = "master"
                        env.FORCE_PRODUCTION = "true"
                    }
                    
                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID,
                            refspec: "+refs/heads/*:refs/remotes/origin/* +refs/tags/*:refs/tags/*"
                        ]],
                        extensions: [[$class: 'LocalBranch', localBranch: "**"]],
                        doGenerateSubmoduleConfigurations: false,
                        submoduleCfg: []
                    ])
                    
                    // Get the actual tag if available
                    def gitTag = sh(
                        script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                        returnStdout: true
                    ).trim()
                    
                    if (gitTag && !tagName) {
                        tagName = gitTag
                        isTagPush = true
                    }
                    
                    env.ACTUAL_BRANCH = branchName
                    env.IS_TAG_PUSH = isTagPush.toString()
                    env.TAG_NAME = tagName ?: ""
                }
            }
        }
        stage('Determine Environment') {
            steps {
                script {
                    def isTagPush = env.IS_TAG_PUSH.toBoolean()
                    
                    if (isTagPush) {
                        // Tag pushes always go to production
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                        echo "🚀 TAG PUSH detected: ${env.TAG_NAME} → Production"
                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        // Master branch push - skip production build or do something else
                        // You can either skip or do a dev build
                        echo "⚠️ Master branch push detected. Production builds only on tags."
                        echo "👉 If you want production, create and push a tag instead."
                        env.SKIP_BUILD = "true"
                        return  // Skip rest of pipeline
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    
                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Tag Push: ${isTagPush}
                    Tag: ${env.TAG_NAME ?: 'N/A'}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    """
                }
            }
        }
        stage('Generate Docker Tag') {
            when {
                expression { return env.SKIP_BUILD != "true" }
            }
            steps {
                script {
                    def isTagPush = env.IS_TAG_PUSH.toBoolean()
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""
                    
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (isTagPush) {
                        // Use the actual tag name
                        imageTag = env.TAG_NAME
                        echo "🎯 Using Git tag for Docker tag: ${imageTag}"
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    } else {
                        imageTag = "prod-${commitId}"
                    }
                    
                    env.IMAGE_TAG = imageTag
                    echo ":rocket: FINAL Docker Tag: ${env.IMAGE_TAG}"
                    currentBuild.description = "${env.DEPLOY_ENV.toUpperCase()} - ${env.IMAGE_TAG}"
                }
            }
        }
        stage('Docker Login') {
            when {
                expression { return env.SKIP_BUILD != "true" }
            }
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }
        stage('Docker Build & Push') {
            when { 
                allOf {
                    expression { return !params.ROLLBACK }
                    expression { return env.SKIP_BUILD != "true" }
                }
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    
                    echo "Building Docker image: ${imageFull}"
                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                    sh "docker logout"
                    
                    // Save build info
                    writeFile file: 'build-info.txt', text: """
                    Build Information
                    -----------------
                    Environment: ${env.DEPLOY_ENV}
                    Docker Tag: ${env.IMAGE_TAG}
                    Git Branch: ${env.ACTUAL_BRANCH}
                    Git Tag: ${env.TAG_NAME ?: 'None'}
                    Commit: ${sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()}
                    Build Time: ${new Date()}
                    """
                    archiveArtifacts artifacts: 'build-info.txt'
                }
            }
        }
    }
    
    post {
        success {
            echo "🎉 Pipeline completed successfully!"
        }
        failure {
            echo "❌ Pipeline failed!"
        }
        always {
            echo "🧹 Cleaning up..."
            sh 'docker system prune -f || true'
        }
    }
}