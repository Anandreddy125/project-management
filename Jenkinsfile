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
        githubPush()
    }
    stages {
        stage('Clean Workspace') {
            steps { cleanWs() }
        }
        
        stage('Checkout Code') {
            steps {
                script {
                    // Determine if this is a tag-based build
                    def isTagBuild = false
                    def ref = env.GIT_BRANCH ?: env.BRANCH_NAME
                    
                    if (ref) {
                        echo "Git Reference: ${ref}"
                        // Check if the ref is a tag (starts with refs/tags/)
                        if (ref.startsWith('refs/tags/') || ref.startsWith('tags/')) {
                            isTagBuild = true
                            env.IS_TAG_BUILD = "true"
                            echo "✅ This is a TAG-based build"
                        } else if (ref.contains('/tags/')) {
                            isTagBuild = true
                            env.IS_TAG_BUILD = "true"
                            echo "✅ This is a TAG-based build"
                        }
                    }
                    
                    // Determine branch for checkout
                    def checkoutBranch = "master"  // Default for tag builds
                    
                    if (isTagBuild) {
                        // Extract tag name from ref
                        def tagName = ref.replace('refs/tags/', '').replace('tags/', '')
                        env.TAG_NAME = tagName
                        echo "📌 Building from tag: ${tagName}"
                        
                        // Force master branch for all tag builds (production)
                        env.ACTUAL_BRANCH = "master"
                        env.BUILD_SOURCE = "TAG"
                        checkoutBranch = "master"
                    } else {
                        // Regular branch build
                        def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                        env.ACTUAL_BRANCH = branchName
                        env.BUILD_SOURCE = "BRANCH"
                        checkoutBranch = branchName
                        
                        echo ":small_blue_diamond: Checking out branch: ${branchName}"
                    }
                    
                    // Perform checkout
                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${checkoutBranch}"]],
                        extensions: [
                            [$class: 'LocalBranch', localBranch: checkoutBranch]
                        ],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID,
                            refspec: "+refs/tags/*:refs/remotes/origin/tags/*"
                        ]]
                    ])
                    
                    // If this is a tag build, checkout the specific tag
                    if (isTagBuild && env.TAG_NAME) {
                        sh """
                            git fetch --all --tags
                            git checkout tags/${env.TAG_NAME} -b build-${env.TAG_NAME}
                        """
                    }
                    
                    // Store commit info
                    env.GIT_COMMIT = sh(script: "git rev-parse HEAD", returnStdout: true).trim()
                    env.GIT_COMMIT_SHORT = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    env.GIT_TAG = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || echo ''", returnStdout: true).trim()
                    
                    echo "Build Info:"
                    echo "  Source: ${env.BUILD_SOURCE}"
                    echo "  Branch: ${env.ACTUAL_BRANCH}"
                    echo "  Commit: ${env.GIT_COMMIT_SHORT}"
                    echo "  Tag: ${env.GIT_TAG ?: 'None'}"
                }
            }
        }
        
        stage('Determine Environment') {
            steps {
                script {
                    // Determine environment based on build source
                    if (env.BUILD_SOURCE == "TAG") {
                        // All tag builds go to production
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                        
                        // Use the actual git tag for image tagging
                        if (env.GIT_TAG) {
                            env.TAG_FOR_DEPLOYMENT = env.GIT_TAG
                        } else {
                            error("Tag build but no git tag found!")
                        }
                        
                    } else if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                        env.TAG_FOR_DEPLOYMENT = "staging-${env.GIT_COMMIT_SHORT}"
                        
                    } else if (env.ACTUAL_BRANCH == "master") {
                        // Master branch build (not from tag)
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                        
                        // For master branch, check if there's a tag on current commit
                        if (env.GIT_TAG) {
                            env.TAG_FOR_DEPLOYMENT = env.GIT_TAG
                        } else {
                            error("Master branch build requires a git tag. Please tag the commit before building.")
                        }
                        
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    
                    echo """
                    Environment Info
                    ----------------------
                    Build Source:  ${env.BUILD_SOURCE}
                    Branch:        ${env.ACTUAL_BRANCH}
                    Deploy Env:    ${env.DEPLOY_ENV}
                    Image:         ${env.IMAGE_NAME}
                    Tag Type:      ${env.TAG_TYPE}
                    Deployment:    ${env.DEPLOYMENT_NAME}
                    Version Tag:   ${env.TAG_FOR_DEPLOYMENT}
                    """
                }
            }
        }
        
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                    } else {
                        // Use the tag determined in previous stage
                        env.IMAGE_TAG = env.TAG_FOR_DEPLOYMENT
                    }
                    
                    echo ":rocket: FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }
        
        stage('Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', 
                        passwordVariable: 'DOCKER_PASSWORD'
                    )]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }
        
        stage('Docker Build & Push') {
            when { 
                expression { 
                    return !params.ROLLBACK 
                } 
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building Docker image: ${imageFull}"
                    
                    // Build with build args if needed
                    sh """
                        docker build \
                            --pull \
                            --no-cache \
                            -t ${imageFull} \
                            --build-arg GIT_COMMIT=${env.GIT_COMMIT} \
                            --build-arg VERSION=${env.IMAGE_TAG} \
                            .
                        
                        docker push ${imageFull}
                    """
                    
                    // Also tag as latest for production
                    if (env.DEPLOY_ENV == "production") {
                        def latestTag = "${env.IMAGE_NAME}:latest"
                        sh """
                            docker tag ${imageFull} ${latestTag}
                            docker push ${latestTag}
                        """
                        echo "✅ Also pushed as 'latest'"
                    }
                    
                    sh "docker logout"
                }
            }
        }
        
        // Add more stages for testing, deployment, etc.
    }
    
    post {
        success {
            script {
                echo "✅ Build ${env.BUILD_NUMBER} completed successfully!"
                echo "📦 Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                echo "🌍 Environment: ${env.DEPLOY_ENV}"
            }
        }
        failure {
            script {
                echo "❌ Build ${env.BUILD_NUMBER} failed!"
            }
        }
    }
}